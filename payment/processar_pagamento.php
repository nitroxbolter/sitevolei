<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$tipo_pagamento = sanitizar($_POST['tipo_pagamento']);
$valor = (float)$_POST['valor'];
$descricao = sanitizar($_POST['descricao']);
$metodo_pagamento = sanitizar($_POST['metodo_pagamento']);
$usuario_id = $_SESSION['user_id'];

// Validações
if (empty($tipo_pagamento) || empty($descricao) || empty($metodo_pagamento)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit();
}

if ($valor <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero']);
    exit();
}

// Gerar ID único para o pagamento
$pagamento_id = 'PAY_' . time() . '_' . rand(1000, 9999);

// Inserir pagamento no banco
$sql = "INSERT INTO pagamentos (usuario_id, tipo_pagamento, valor, descricao, metodo_pagamento, status, pagamento_id) 
        VALUES (?, ?, ?, ?, ?, 'Pendente', ?)";
$result = executeQuery($pdo, $sql, [$usuario_id, $tipo_pagamento, $valor, $descricao, $metodo_pagamento, $pagamento_id]);

if ($result) {
    // Aqui você integraria com o gateway de pagamento real
    // Por enquanto, vamos simular um processamento
    
    $response = [
        'success' => true,
        'message' => 'Pagamento processado com sucesso!',
        'pagamento_id' => $pagamento_id,
        'valor' => $valor,
        'metodo' => $metodo_pagamento
    ];
    
    // Simular diferentes respostas baseadas no método de pagamento
    switch ($metodo_pagamento) {
        case 'pix':
            $response['qr_code'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
            $response['codigo_pix'] = '00020126580014br.gov.bcb.pix0136' . $pagamento_id;
            break;
        case 'cartao':
        case 'debito':
            $response['message'] = 'Pagamento aprovado!';
            // Atualizar status para aprovado
            executeQuery($pdo, "UPDATE pagamentos SET status = 'Aprovado' WHERE pagamento_id = ?", [$pagamento_id]);
            break;
        case 'boleto':
            $response['codigo_barras'] = '23793' . str_pad($pagamento_id, 25, '0', STR_PAD_LEFT);
            $response['vencimento'] = date('d/m/Y', strtotime('+3 days'));
            break;
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento']);
}
?>
