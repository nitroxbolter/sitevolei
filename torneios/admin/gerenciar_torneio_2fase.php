<?php
// Arquivo da 2ª Fase do Torneio Pro
// Este arquivo contém todo o código relacionado à 2ª fase do torneio

// Verificar se todas as partidas da 1ª fase estão finalizadas
$sql_partidas_1fase = "SELECT COUNT(*) as total, 
                      SUM(CASE WHEN status = 'Finalizada' THEN 1 ELSE 0 END) as finalizadas
                      FROM torneio_partidas 
                      WHERE torneio_id = ? AND (fase = 'Grupos' OR fase IS NULL OR fase = '')";
$stmt_partidas_1fase = executeQuery($pdo, $sql_partidas_1fase, [$torneio_id]);
$info_1fase = $stmt_partidas_1fase ? $stmt_partidas_1fase->fetch() : ['total' => 0, 'finalizadas' => 0];
$todas_1fase_finalizadas = $info_1fase['total'] > 0 && $info_1fase['finalizadas'] == $info_1fase['total'];

// TODO: Adicionar aqui todo o código da 2ª fase que está no arquivo principal
// Este arquivo precisa ser preenchido com o conteúdo das linhas 2028-4077 do arquivo original
?>

