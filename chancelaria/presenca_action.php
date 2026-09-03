<?php
// Inicia a sessão para pegar o tenant_id com segurança
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../configuracoes/config.php'; // Ajuste o caminho conforme necessário

// Proteção para garantir que é um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método inválido.']);
    exit;
}

// Pega o tenant_id direto da sessão do usuário logado (Mais seguro do que via POST)
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada ou acesso negado.']);
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$sessao_id = (int)($_POST['sessao_id'] ?? 0);
$membro_id = (int)($_POST['membro_id'] ?? 0);
$acao      = $_POST['acao'] ?? 'adicionar'; // Pega a ação, se não vier, assume que é adicionar
$status    = $_POST['status'] ?? 'P';

if ($sessao_id === 0 || $membro_id === 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos enviados.']);
    exit;
}

try {
    if ($acao === 'remover') {
        // Se a ação for remover, apaga a presença especificamente DESTE membro NESTA sessão
        $stmtDel = $pdo->prepare("DELETE FROM chancelaria_presencas WHERE tenant_id = ? AND sessao_id = ? AND membro_id = ?");
        $stmtDel->execute([$tenant_id, $sessao_id, $membro_id]);
        
        echo json_encode(['sucesso' => true]);

    } else {
        // Ação de ADICIONAR (Vinda do Modal)
        
        // 1. Verifica se o obreiro já está registrado para não gerar o erro "Duplicate entry 1062"
        $stmtCheck = $pdo->prepare("SELECT id FROM chancelaria_presencas WHERE tenant_id = ? AND sessao_id = ? AND membro_id = ?");
        $stmtCheck->execute([$tenant_id, $sessao_id, $membro_id]);
        
        if ($stmtCheck->rowCount() > 0) {
            // Já tem presença
            echo json_encode(['sucesso' => false, 'mensagem' => 'Este Obreiro já está com presença registrada nesta sessão.']);
            exit;
        }

        // 2. Se não tem, insere a presença
        $stmtIns = $pdo->prepare("INSERT INTO chancelaria_presencas (tenant_id, sessao_id, membro_id, status_presenca) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$tenant_id, $sessao_id, $membro_id, $status]);

        echo json_encode(['sucesso' => true]);
    }

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro BD: ' . $e->getMessage()]);
}
?>