<?php
session_start();
require '../configuracoes/config.php';

// 1. TRAVA DE SEGURANÇA: Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado. Faça login para continuar.");
}

$usuario_id = $_SESSION['user_id']; // Pega o ID do dono do sistema logado

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chave = filter_input(INPUT_POST, 'chave_pix', FILTER_SANITIZE_STRING);
    $titular = filter_input(INPUT_POST, 'titular', FILTER_SANITIZE_STRING);
    $cidade = filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_STRING);

    try {
        // 2. Insere ou Atualiza APENAS para o usuario_id logado
        $stmt = $pdo->prepare("
            INSERT INTO configuracoes_pix (usuario_id, chave_pix, titular, cidade) 
            VALUES (:usuario_id, :chave, :titular, :cidade)
            ON DUPLICATE KEY UPDATE 
            chave_pix = VALUES(chave_pix), 
            titular = VALUES(titular), 
            cidade = VALUES(cidade)
        ");
        
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':chave' => $chave,
            ':titular' => $titular,
            ':cidade' => $cidade
        ]);
        
        $_SESSION['msg_sucesso'] = "Configurações do PIX atualizadas com sucesso!";
        header("Location: config_pix.php");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar: " . $e->getMessage());
    }
}
?>