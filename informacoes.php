<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$titulo = 'Informações';
$secao = isset($_GET['secao']) ? $_GET['secao'] : 'quadras';

// Verificar se é administrador
$is_admin = isLoggedIn() && isAdmin($pdo, $_SESSION['user_id']);

include 'includes/header.php';
?>

<div class="row">
    <!-- Menu Lateral -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bars me-2"></i>Menu</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="?secao=quadras" class="list-group-item list-group-item-action <?php echo $secao === 'quadras' ? 'active' : ''; ?>">
                    <i class="fas fa-volleyball-ball me-2"></i>Quadras
                </a>
                <a href="?secao=profissionais" class="list-group-item list-group-item-action <?php echo $secao === 'profissionais' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie me-2"></i>Profissionais
                </a>
                <a href="?secao=dicas" class="list-group-item list-group-item-action <?php echo $secao === 'dicas' ? 'active' : ''; ?>">
                    <i class="fas fa-lightbulb me-2"></i>Dicas
                </a>
                <a href="?secao=galeria" class="list-group-item list-group-item-action <?php echo $secao === 'galeria' ? 'active' : ''; ?>">
                    <i class="fas fa-images me-2"></i>Galeria de Fotos
                </a>
                <a href="?secao=atualizacoes" class="list-group-item list-group-item-action <?php echo $secao === 'atualizacoes' ? 'active' : ''; ?>">
                    <i class="fas fa-sync-alt me-2"></i>Atualizações do Site
                </a>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="col-md-9">
        <?php if ($secao === 'quadras'): ?>
            <!-- Seção Quadras -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-volleyball-ball me-2"></i>Quadras</h4>
                    <?php if ($is_admin): ?>
                        <a href="admin/informacoes/quadras.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>Adicionar Quadra
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Buscar quadras do banco de dados
                    $sql_quadras = "SELECT * FROM quadras WHERE ativo = 1 ORDER BY nome ASC";
                    $stmt_quadras = executeQuery($pdo, $sql_quadras);
                    $quadras = $stmt_quadras ? $stmt_quadras->fetchAll() : [];
                    ?>
                    
                    <?php if (empty($quadras)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nenhuma quadra cadastrada no momento.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($quadras as $quadra): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm">
                                        <?php if (!empty($quadra['foto'])): ?>
                                            <?php
                                            $imagem = $quadra['foto'];
                                            if (strpos($imagem, '/') !== 0) {
                                                $imagem = '/' . $imagem;
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($imagem); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($quadra['nome']); ?>"
                                                 style="height: 200px; width: 100%; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-volleyball-ball me-2"></i>
                                                <?php echo htmlspecialchars($quadra['nome']); ?>
                                            </h5>
                                            <p class="card-text">
                                                <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                                                <strong>Endereço:</strong> <?php echo htmlspecialchars($quadra['endereco']); ?>
                                            </p>
                                            <p class="card-text">
                                                <i class="fas fa-dollar-sign me-2 text-success"></i>
                                                <strong>Valor por hora:</strong> R$ <?php echo number_format($quadra['valor_hora'], 2, ',', '.'); ?>
                                            </p>
                                            <?php if (!empty($quadra['descricao'])): ?>
                                                <p class="card-text">
                                                    <?php echo nl2br(htmlspecialchars($quadra['descricao'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="card-text">
                                                <i class="fas fa-tag me-2 text-info"></i>
                                                <strong>Tipo:</strong> 
                                                <span class="badge bg-<?php echo $quadra['tipo'] === 'areia' ? 'warning text-dark' : 'primary'; ?>">
                                                    <?php echo ucfirst($quadra['tipo']); ?>
                                                </span>
                                            </p>
                                            <?php if (!empty($quadra['localizacao'])): ?>
                                                <p class="card-text">
                                                    <i class="fas fa-map me-2 text-primary"></i>
                                                    <strong>Localização:</strong> 
                                                    <a href="<?php echo htmlspecialchars($quadra['localizacao']); ?>" target="_blank" class="text-decoration-none">
                                                        Ver no mapa <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($is_admin): ?>
                                                <div class="mt-3">
                                                    <a href="admin/informacoes/quadras.php?id=<?php echo $quadra['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($secao === 'profissionais'): ?>
            <!-- Seção Profissionais -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-user-tie me-2"></i>Profissionais</h4>
                    <?php if ($is_admin): ?>
                        <a href="admin/informacoes/profissionais.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>Adicionar Profissional
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Buscar profissionais do banco de dados
                    $sql_profissionais = "SELECT * FROM profissionais WHERE ativo = 1 ORDER BY nome ASC";
                    $stmt_profissionais = executeQuery($pdo, $sql_profissionais);
                    $profissionais = $stmt_profissionais ? $stmt_profissionais->fetchAll() : [];
                    ?>
                    
                    <?php if (empty($profissionais)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nenhum profissional cadastrado no momento.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($profissionais as $profissional): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-user-tie me-2"></i>
                                                <?php echo htmlspecialchars($profissional['nome']); ?>
                                            </h5>
                                            <?php if (!empty($profissional['modalidade'])): ?>
                                                <p class="card-text">
                                                    <i class="fas fa-star me-2 text-warning"></i>
                                                    <strong>Modalidade:</strong> <?php echo htmlspecialchars($profissional['modalidade']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($profissional['telefone'])): ?>
                                                <p class="card-text">
                                                    <i class="fas fa-phone me-2 text-success"></i>
                                                    <strong>Telefone:</strong> <?php echo htmlspecialchars($profissional['telefone']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($profissional['email'])): ?>
                                                <p class="card-text">
                                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($profissional['email']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($profissional['descricao'])): ?>
                                                <p class="card-text">
                                                    <?php echo nl2br(htmlspecialchars($profissional['descricao'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($is_admin): ?>
                                                <div class="mt-3">
                                                    <a href="admin/informacoes/profissionais.php?id=<?php echo $profissional['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($secao === 'dicas'): ?>
            <!-- Seção Dicas -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Dicas</h4>
                    <?php if ($is_admin): ?>
                        <a href="admin/informacoes/dicas.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>Adicionar Dica
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Buscar dicas do banco de dados
                    $sql_dicas = "SELECT * FROM dicas WHERE ativo = 1 ORDER BY data_criacao DESC";
                    $stmt_dicas = executeQuery($pdo, $sql_dicas);
                    $dicas = $stmt_dicas ? $stmt_dicas->fetchAll() : [];
                    ?>
                    
                    <?php if (empty($dicas)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nenhuma dica cadastrada no momento.
                        </div>
                    <?php else: ?>
                        <?php foreach ($dicas as $dica): ?>
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-lightbulb me-2 text-warning"></i>
                                        <?php echo htmlspecialchars($dica['titulo']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <?php echo nl2br(htmlspecialchars($dica['conteudo'])); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if (!empty($dica['data_criacao'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatarData($dica['data_criacao'], 'd/m/Y'); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <a href="admin/informacoes/dicas.php?id=<?php echo $dica['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i>Editar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($secao === 'galeria'): ?>
            <!-- Seção Galeria -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-images me-2"></i>Galeria de Fotos da Comunidade</h4>
                    <?php if ($is_admin): ?>
                        <a href="admin/informacoes/galeria.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>Adicionar Foto
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Buscar fotos do banco de dados
                    $sql_fotos = "SELECT * FROM galeria_fotos WHERE ativo = 1 ORDER BY data_upload DESC";
                    $stmt_fotos = executeQuery($pdo, $sql_fotos);
                    $fotos = $stmt_fotos ? $stmt_fotos->fetchAll() : [];
                    ?>
                    
                    <?php if (empty($fotos)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nenhuma foto cadastrada no momento.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($fotos as $foto): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card shadow-sm">
                                        <img src="<?php echo htmlspecialchars($foto['caminho']); ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($foto['titulo'] ?? 'Foto'); ?>"
                                             style="height: 200px; object-fit: cover;">
                                        <div class="card-body">
                                            <?php if (!empty($foto['titulo'])): ?>
                                                <h6 class="card-title"><?php echo htmlspecialchars($foto['titulo']); ?></h6>
                                            <?php endif; ?>
                                            <?php if (!empty($foto['descricao'])): ?>
                                                <p class="card-text small"><?php echo htmlspecialchars($foto['descricao']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($is_admin): ?>
                                                <div class="mt-2">
                                                    <a href="admin/informacoes/galeria.php?id=<?php echo $foto['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($secao === 'atualizacoes'): ?>
            <!-- Seção Atualizações -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Atualizações do Site</h4>
                    <?php if ($is_admin): ?>
                        <a href="admin/informacoes/atualizacoes.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>Adicionar Atualização
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php
                    // Buscar atualizações do banco de dados
                    $sql_atualizacoes = "SELECT * FROM atualizacoes_site WHERE ativo = 1 ORDER BY data_publicacao DESC";
                    $stmt_atualizacoes = executeQuery($pdo, $sql_atualizacoes);
                    $atualizacoes = $stmt_atualizacoes ? $stmt_atualizacoes->fetchAll() : [];
                    ?>
                    
                    <?php if (empty($atualizacoes)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Nenhuma atualização registrada no momento.
                        </div>
                    <?php else: ?>
                        <?php foreach ($atualizacoes as $atualizacao): ?>
                            <div class="card mb-3 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-sync-alt me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($atualizacao['titulo']); ?>
                                        <?php if (!empty($atualizacao['versao'])): ?>
                                            <span class="badge bg-info ms-2">v<?php echo htmlspecialchars($atualizacao['versao']); ?></span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="card-text">
                                        <?php echo nl2br(htmlspecialchars($atualizacao['descricao'])); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if (!empty($atualizacao['data_publicacao'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatarData($atualizacao['data_publicacao'], 'd/m/Y H:i'); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <a href="admin/informacoes/atualizacoes.php?id=<?php echo $atualizacao['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit me-1"></i>Editar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

