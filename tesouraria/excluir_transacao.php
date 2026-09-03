<?php
session_start();
require '../configuracoes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID não fornecido.']);
        exit;
    }

    try {
        // Exclui a transação GARANTINDO que pertence ao usuário logado
        $sql = "DELETE FROM transacoes WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $user_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Transação não encontrada ou você não tem permissão.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no banco: ' . $e->getMessage()]);
    }
}
?>
