<?php
require '/var/www/html3-current/includes/db_connect.php';

$tables = [
    'sistema_pontuacao_pontos',
    'sistema_pontuacao_participantes',
    'sistema_pontuacao_jogos',
];

foreach ($tables as $table) {
    echo "TABLE {$table}\n";
    foreach ($pdo->query("SHOW INDEX FROM {$table}") as $row) {
        echo $row['Key_name'] . '|' . $row['Column_name'] . '|nonuniq=' . $row['Non_unique'] . "\n";
    }
}

echo "DUP pontos\n";
foreach ($pdo->query("SELECT jogo_id, usuario_id, COUNT(*) c, SUM(pontos) total FROM sistema_pontuacao_pontos GROUP BY jogo_id, usuario_id HAVING c > 1 LIMIT 20") as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "DUP participantes\n";
foreach ($pdo->query("SELECT jogo_id, usuario_id, COUNT(*) c FROM sistema_pontuacao_participantes GROUP BY jogo_id, usuario_id HAVING c > 1 LIMIT 20") as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "ORFAOS\n";
foreach ($pdo->query("SELECT p.jogo_id, p.usuario_id, p.pontos FROM sistema_pontuacao_pontos p LEFT JOIN sistema_pontuacao_participantes pp ON pp.jogo_id = p.jogo_id AND pp.usuario_id = p.usuario_id WHERE pp.id IS NULL LIMIT 20") as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
