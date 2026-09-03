<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../configuracoes/config.php';

if (!isset($_SESSION['tenant_id'])) {
    die("Acesso negado.");
}
$tenant_id = $_SESSION['tenant_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $data_sessao = $_POST['data_sessao'] ?? '';
    $hora_sessao = $_POST['hora_sessao'] ?? '';
    $tipo = $_POST['tipo'] ?? 'Ordinária';
    $grau = $_POST['grau'] ?? '1';
    $status = $_POST['status'] ?? 'Realizada';
    $token_presenca = bin2hex(random_bytes(16));

    if (!empty($titulo) && !empty($data_sessao)) {
        try {
            if (!empty($id)) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE chancelaria_sessoes SET titulo = ?, data_sessao = ?, hora_sessao = ?, tipo = ?, grau = ?, status = ?, token_presenca = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$titulo, $data_sessao, $hora_sessao, $tipo, $grau, $status, $id, $tenant_id, $token_presenca]);
                $_SESSION['mensagem'] = "Sessão atualizada com sucesso!";
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO chancelaria_sessoes (tenant_id, titulo, data_sessao, hora_sessao, tipo, grau, status, token_presenca) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $titulo, $data_sessao, $hora_sessao, $tipo, $grau, $status, $token_presenca]);
                $_SESSION['mensagem'] = "Sessão cadastrada com sucesso!";
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao salvar sessão: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha os campos obrigatórios.";
    }

    header("Location: sessoes");
    exit;
}
