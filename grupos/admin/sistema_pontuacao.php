<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '../../admin/sistema_pontuacao.php';

if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 302);
exit();
