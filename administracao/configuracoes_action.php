<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum dado recebido.']);
    exit;
}

// Limpa e extrai os dados
$whatsapp_grupo_id = trim($dados['whatsapp_grupo_id'] ?? '');

try {
    // Atualiza a tabela usuarios garantindo que o update ocorra apenas para o tenant logado
    $sql = "UPDATE usuarios SET whatsapp_grupo_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$whatsapp_grupo_id, $tenant_id]);

    echo json_encode(['sucesso' => true, 'mensagem' => 'Configurações salvas com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>