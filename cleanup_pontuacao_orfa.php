<?php
require '/var/www/html3-current/includes/db_connect.php';

$sql = "DELETE p
        FROM sistema_pontuacao_pontos p
        LEFT JOIN sistema_pontuacao_participantes pp
            ON pp.jogo_id = p.jogo_id
           AND pp.usuario_id = p.usuario_id
        WHERE pp.id IS NULL";

$affected = $pdo->exec($sql);
echo "orphan_points_deleted=" . (int)$affected . "\n";
