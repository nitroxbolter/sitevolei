<?php
/**
 * Script para popular a tabela torneio_classificacao_2fase
 * com os dados da classificação da 2ª fase que já existem na tabela torneio_classificacao
 */

session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit();
}

$torneio_id = (int)($_POST['torneio_id'] ?? $_GET['torneio_id'] ?? 0);
if ($torneio_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Torneio inválido.']);
    exit();
}

try {
    // Verificar se a tabela existe
    $sql_check_table = "SHOW TABLES LIKE 'torneio_classificacao_2fase'";
    $stmt_check = $pdo->query($sql_check_table);
    $table_exists = $stmt_check && $stmt_check->rowCount() > 0;
    
    if (!$table_exists) {
        echo json_encode(['success' => false, 'message' => 'A tabela torneio_classificacao_2fase não existe. Execute o script SQL primeiro.']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Buscar todos os grupos da 2ª fase
    $sql_grupos_2fase = "SELECT id, nome FROM torneio_grupos 
                        WHERE torneio_id = ? 
                        AND nome LIKE '2ª Fase%'
                        AND (nome LIKE '%Ouro A%' OR nome LIKE '%Ouro B%' 
                             OR nome LIKE '%Prata A%' OR nome LIKE '%Prata B%'
                             OR nome LIKE '%Bronze A%' OR nome LIKE '%Bronze B%')";
    $stmt_grupos = executeQuery($pdo, $sql_grupos_2fase, [$torneio_id]);
    $grupos_2fase = $stmt_grupos ? $stmt_grupos->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $total_inseridos = 0;
    $total_atualizados = 0;
    
    foreach ($grupos_2fase as $grupo) {
        $grupo_id = (int)$grupo['id'];
        $nome_grupo = $grupo['nome'];
        
        // Identificar série
        $serie_nome = null;
        if (preg_match('/Ouro\s+A/i', $nome_grupo)) {
            $serie_nome = 'Ouro A';
        } elseif (preg_match('/Ouro\s+B/i', $nome_grupo)) {
            $serie_nome = 'Ouro B';
        } elseif (preg_match('/Prata\s+A/i', $nome_grupo)) {
            $serie_nome = 'Prata A';
        } elseif (preg_match('/Prata\s+B/i', $nome_grupo)) {
            $serie_nome = 'Prata B';
        } elseif (preg_match('/Bronze\s+A/i', $nome_grupo)) {
            $serie_nome = 'Bronze A';
        } elseif (preg_match('/Bronze\s+B/i', $nome_grupo)) {
            $serie_nome = 'Bronze B';
        }
        
        if (!$serie_nome) {
            continue;
        }
        
        // Buscar classificação deste grupo da tabela torneio_classificacao
        $sql_classificacao = "SELECT 
                                tc.time_id,
                                tc.vitorias,
                                tc.derrotas,
                                tc.empates,
                                tc.pontos_pro,
                                tc.pontos_contra,
                                tc.saldo_pontos,
                                tc.average,
                                tc.pontos_total
                             FROM torneio_classificacao tc
                             WHERE tc.torneio_id = ? AND tc.grupo_id = ?
                             ORDER BY tc.pontos_total DESC, tc.vitorias DESC, tc.average DESC, tc.saldo_pontos DESC";
        
        $stmt_class = executeQuery($pdo, $sql_classificacao, [$torneio_id, $grupo_id]);
        $classificacao = $stmt_class ? $stmt_class->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $posicao = 1;
        foreach ($classificacao as $class) {
            $time_id = (int)$class['time_id'];
            
            // Verificar se já existe
            $sql_check = "SELECT id FROM torneio_classificacao_2fase 
                         WHERE torneio_id = ? AND serie = ? AND time_id = ?";
            $stmt_check = executeQuery($pdo, $sql_check, [$torneio_id, $serie_nome, $time_id]);
            $exists = $stmt_check ? $stmt_check->fetch() : null;
            
            if ($exists) {
                // Atualizar
                $sql_update = "UPDATE torneio_classificacao_2fase 
                              SET vitorias = ?, derrotas = ?, empates = ?, 
                                  pontos_pro = ?, pontos_contra = ?, saldo_pontos = ?, 
                                  average = ?, pontos_total = ?, posicao = ?
                              WHERE torneio_id = ? AND serie = ? AND time_id = ?";
                executeQuery($pdo, $sql_update, [
                    (int)$class['vitorias'],
                    (int)$class['derrotas'],
                    (int)$class['empates'],
                    (int)$class['pontos_pro'],
                    (int)$class['pontos_contra'],
                    (int)$class['saldo_pontos'],
                    (float)$class['average'],
                    (int)$class['pontos_total'],
                    $posicao,
                    $torneio_id,
                    $serie_nome,
                    $time_id
                ]);
                $total_atualizados++;
            } else {
                // Inserir
                $sql_insert = "INSERT INTO torneio_classificacao_2fase 
                              (torneio_id, serie, time_id, vitorias, derrotas, empates, 
                               pontos_pro, pontos_contra, saldo_pontos, average, pontos_total, posicao)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                executeQuery($pdo, $sql_insert, [
                    $torneio_id,
                    $serie_nome,
                    $time_id,
                    (int)$class['vitorias'],
                    (int)$class['derrotas'],
                    (int)$class['empates'],
                    (int)$class['pontos_pro'],
                    (int)$class['pontos_contra'],
                    (int)$class['saldo_pontos'],
                    (float)$class['average'],
                    (int)$class['pontos_total'],
                    $posicao
                ]);
                $total_inseridos++;
            }
            
            $posicao++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Tabela populada com sucesso! Inseridos: $total_inseridos, Atualizados: $total_atualizados",
        'inseridos' => $total_inseridos,
        'atualizados' => $total_atualizados
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao popular tabela: ' . $e->getMessage()
    ]);
}
?>

