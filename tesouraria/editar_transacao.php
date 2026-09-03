<?php
// FORÇAR EXIBIÇÃO DE ERROS (Isso fará o erro parar de ser "empty string")
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

session_start();
require '../configuracoes/config.php'; // Verifique se o caminho do seu config está correto aqui
header('Content-Type: application/json');

if (!isset($_SESSION['tenant_id']) && !isset($_SESSION['user'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada.']);
    exit;
}

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $descricao = $_POST['descricao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $valor = $_POST['valor'] ?? 0;

    if (!$id || empty($descricao) || empty($tipo)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos.']);
        exit;
    }

    try {
        $sql = "UPDATE transacoes SET descricao = ?, tipo = ?, valor = ? WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([$descricao, $tipo, $valor, $id, $user_id]);

        if ($resultado && $stmt->rowCount() > 0) {
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum dado modificado ou você não tem permissão.']);
        }
    } catch (PDOException $e) {
        // Se der erro no banco, agora ele vai cuspir o erro na tela
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no banco: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido.']);
}
?>
